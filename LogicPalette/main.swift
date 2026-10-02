import Cocoa
import ApplicationServices
import ServiceManagement
import Carbon

// MARK: - Palette data (Logic Pro 10.8 "Color" window: 24 hues x 4 brightness rows)

let logicColors: [[UInt32]] = [
    [0xB94624, 0xBB6029, 0xBE7F30, 0xC3A539, 0xC9C943, 0xACC741, 0x91C73F, 0x7AC63E, 0x5FC64A, 0x5FC667, 0x5FC685, 0x5FC6A6, 0x60C6C8, 0x4EA2C5, 0x4D95CA, 0x4C81CD, 0x4867CF, 0x4647D1, 0x5A45CE, 0x6F40CC, 0x8439CA, 0x962AC2, 0xB830C3, 0xB82FA0],
    [0x964125, 0x985528, 0x9A6C2D, 0x9E8833, 0xA3A23A, 0x8DA238, 0x79A137, 0x67A036, 0x4FA042, 0x4FA05A, 0x4FA071, 0x4FA089, 0x4FA1A2, 0x4286A0, 0x437BA2, 0x466EA6, 0x445BA8, 0x4343AA, 0x5141A7, 0x603DA5, 0x7138A5, 0x7C289E, 0x952C9E, 0x952B84],
    [0x733824, 0x754726, 0x775729, 0x7A6A2C, 0x7D7C30, 0x6E7C2F, 0x607B2F, 0x527B2E, 0x3F7B38, 0x3F7B49, 0x3F7B5A, 0x3F7B6B, 0x3F7B7C, 0x37697B, 0x39627D, 0x3B587F, 0x3B4B80, 0x3A3A80, 0x453A80, 0x4F377E, 0x5A337E, 0x612679, 0x722879, 0x722767],
    [0x502C1F, 0x513520, 0x533F21, 0x554B23, 0x575625, 0x4D5625, 0x445624, 0x3C5524, 0x2E552B, 0x2E5536, 0x2E5541, 0x2E554B, 0x2E5556, 0x294A55, 0x2C4657, 0x2F415A, 0x2E3859, 0x2E2E59, 0x342D59, 0x3B2C59, 0x402956, 0x452054, 0x4F2154, 0x4F2049],
]
let logicRows = 4
let logicCols = 24

func nsColor(_ hex: UInt32) -> NSColor {
    NSColor(srgbRed: CGFloat((hex >> 16) & 0xFF) / 255,
            green: CGFloat((hex >> 8) & 0xFF) / 255,
            blue: CGFloat(hex & 0xFF) / 255, alpha: 1)
}

// MARK: - Log

let logURL = FileManager.default.homeDirectoryForCurrentUser
    .appendingPathComponent("Library/Logs/LogicPalette.log")

func log(_ s: String) {
    let line = "\(Date()) \(s)\n"
    if let h = try? FileHandle(forWritingTo: logURL) {
        h.seekToEndOfFile(); h.write(line.data(using: .utf8)!); try? h.close()
    } else {
        try? line.data(using: .utf8)!.write(to: logURL)
    }
}

// MARK: - Accessibility helpers

func axAttr<T>(_ el: AXUIElement, _ name: String) -> T? {
    var v: CFTypeRef?
    guard AXUIElementCopyAttributeValue(el, name as CFString, &v) == .success else { return nil }
    return v as? T
}

func axFrame(_ el: AXUIElement) -> CGRect? {
    guard let p: AXValue = axAttr(el, kAXPositionAttribute),
          let s: AXValue = axAttr(el, kAXSizeAttribute) else { return nil }
    var pt = CGPoint.zero, sz = CGSize.zero
    AXValueGetValue(p, .cgPoint, &pt)
    AXValueGetValue(s, .cgSize, &sz)
    return CGRect(origin: pt, size: sz)
}

func axChildren(_ el: AXUIElement) -> [AXUIElement] {
    axAttr(el, kAXChildrenAttribute) ?? []
}

func axActions(_ el: AXUIElement) -> [String] {
    var names: CFArray?
    guard AXUIElementCopyActionNames(el, &names) == .success else { return [] }
    return (names as? [String]) ?? []
}

// MARK: - Logic bridge

final class LogicBridge {
    static let bundleID = "com.apple.logic10"
    static let colorWindowTitles: Set<String> = ["Color", "Colors"]

    // Swatch centers relative to the top-left corner of Logic's "Color" window (points),
    // measured from Logic Pro 10.8.
    static let firstSwatch = CGPoint(x: 59, y: 46.5)
    static let swatchPitch = CGSize(width: 28, height: 20)
    static let resetButton = CGPoint(x: 31, y: 46.5)

    let queue = DispatchQueue(label: "LogicPalette.bridge")
    var busy = false

    var logicApp: NSRunningApplication? {
        NSRunningApplication.runningApplications(withBundleIdentifier: Self.bundleID).first
    }

    func colorWindow(_ app: AXUIElement) -> AXUIElement? {
        let windows: [AXUIElement] = axAttr(app, kAXWindowsAttribute) ?? []
        for w in windows {
            if let t: String = axAttr(w, kAXTitleAttribute), Self.colorWindowTitles.contains(t) { return w }
        }
        return nil
    }

    /// "Show Colors" item in Logic's View menu.
    func colorsMenuItem(_ app: AXUIElement) -> AXUIElement? {
        guard let bar: AXUIElement = axAttr(app, kAXMenuBarAttribute) else { return nil }
        var stack: [(AXUIElement, Int)] = [(bar, 0)]
        while let (el, depth) = stack.popLast() {
            if let t: String = axAttr(el, kAXTitleAttribute), t == "Show Colors" { return el }
            if depth < 4 { stack += axChildren(el).map { ($0, depth + 1) } }
        }
        log("'Show Colors' menu item not found")
        return nil
    }

    /// Individual swatch elements, if Logic exposes them, ordered [row][col].
    func swatchElements(_ window: AXUIElement) -> [[AXUIElement]]? {
        var found: [(AXUIElement, CGRect)] = []
        var stack: [(AXUIElement, Int)] = [(window, 0)]
        while let (el, depth) = stack.popLast() {
            if let f = axFrame(el), (20...27).contains(f.width), (12...18).contains(f.height) {
                found.append((el, f))
            }
            if depth < 6 { stack += axChildren(el).map { ($0, depth + 1) } }
        }
        guard found.count == logicRows * logicCols else { return nil }
        found.sort { abs($0.1.minY - $1.1.minY) > 4 ? $0.1.minY < $1.1.minY : $0.1.minX < $1.1.minX }
        return (0..<logicRows).map { r in found[(r * logicCols)..<((r + 1) * logicCols)].map { $0.0 } }
    }

    func postKey(_ code: CGKeyCode, flags: CGEventFlags) {
        let src = CGEventSource(stateID: .hidSystemState)
        for down in [true, false] {
            let e = CGEvent(keyboardEventSource: src, virtualKey: code, keyDown: down)
            e?.flags = flags
            e?.post(tap: .cghidEventTap)
            usleep(8000)
        }
    }

    /// Logic matches key commands by character, so ⌥C only works with a Latin layout active.
    func withASCIIKeyboard(_ body: () -> Void) {
        func sourceID(_ s: TISInputSource) -> String {
            guard let p = TISGetInputSourceProperty(s, kTISPropertyInputSourceID) else { return "" }
            return Unmanaged<CFString>.fromOpaque(p).takeUnretainedValue() as String
        }
        var previous: TISInputSource?
        DispatchQueue.main.sync {
            let current = TISCopyCurrentKeyboardInputSource().takeRetainedValue()
            let ascii = TISCopyCurrentASCIICapableKeyboardLayoutInputSource().takeRetainedValue()
            if sourceID(current) != sourceID(ascii) {
                previous = current
                TISSelectInputSource(ascii)
            }
        }
        if previous != nil { usleep(60000) }
        body()
        if let p = previous {
            usleep(60000)
            DispatchQueue.main.sync { _ = TISSelectInputSource(p) }
        }
    }

    func toggleColorsWindowByKey() {
        withASCIIKeyboard { postKey(8, flags: .maskAlternate) } // ⌥C — Show/Hide Colors
    }

    func click(at p: CGPoint) {
        let saved = CGEvent(source: nil)?.location ?? p
        let src = CGEventSource(stateID: .hidSystemState)
        func post(_ type: CGEventType, _ at: CGPoint) {
            let e = CGEvent(mouseEventSource: src, mouseType: type, mouseCursorPosition: at, mouseButton: .left)
            e?.setIntegerValueField(.mouseEventClickState, value: 1)
            e?.post(tap: .cghidEventTap)
        }
        post(.mouseMoved, p); usleep(15000)
        post(.leftMouseDown, p); usleep(20000)
        post(.leftMouseUp, p); usleep(15000)
        CGWarpMouseCursorPosition(saved)
        CGAssociateMouseAndMouseCursorPosition(1)
    }

    func waitFor<T>(_ timeout: TimeInterval, _ probe: () -> T?) -> T? {
        let end = Date().addingTimeInterval(timeout)
        repeat {
            if let v = probe() { return v }
            usleep(30000)
        } while Date() < end
        return nil
    }

    /// row/col in Logic's layout; nil = Logic's reset ("default color") button.
    func apply(row: Int?, col: Int?) {
        guard !busy else { return }
        guard AXIsProcessTrusted() else {
            log("not trusted for Accessibility")
            NSSound.beep()
            DispatchQueue.main.async { promptAccessibilityOnce() }
            return
        }
        guard let app = logicApp else { log("Logic Pro is not running"); NSSound.beep(); return }
        busy = true
        queue.async { [self] in
            defer { busy = false }

            if !app.isActive {
                app.activate(options: [])
                _ = waitFor(1.0) { app.isActive ? true : nil }
                usleep(80000)
            }

            let axApp = AXUIElementCreateApplication(app.processIdentifier)
            var window = colorWindow(axApp)
            let openedByUs = window == nil
            if openedByUs {
                toggleColorsWindowByKey()
                window = waitFor(0.8) { colorWindow(axApp) }
                if window == nil, let item = colorsMenuItem(axApp) {
                    AXUIElementPerformAction(item, kAXPressAction as CFString)
                    window = waitFor(0.8) { colorWindow(axApp) }
                }
            }
            guard let win = window, let wf = axFrame(win) else {
                log("Color window not found (opened by us: \(openedByUs))")
                DispatchQueue.main.async { NSSound.beep() }
                return
            }
            if openedByUs { usleep(60000) }

            if let r = row, let c = col, let grid = swatchElements(win), let f = axFrame(grid[r][c]) {
                click(at: CGPoint(x: f.midX, y: f.midY))
                log("click swatch element r\(r) c\(c) at \(f), window \(wf)")
            } else {
                let p: CGPoint
                if let r = row, let c = col {
                    p = CGPoint(x: wf.minX + Self.firstSwatch.x + CGFloat(c) * Self.swatchPitch.width,
                                y: wf.minY + Self.firstSwatch.y + CGFloat(r) * Self.swatchPitch.height)
                } else {
                    p = CGPoint(x: wf.minX + Self.resetButton.x, y: wf.minY + Self.resetButton.y)
                }
                click(at: p)
                log("click r\(row.map(String.init) ?? "reset") c\(col.map(String.init) ?? "-") at \(p), window \(wf)")
            }

            if openedByUs {
                usleep(60000)
                if let close: AXUIElement = axAttr(win, kAXCloseButtonAttribute),
                   AXUIElementPerformAction(close, kAXPressAction as CFString) == .success {
                } else {
                    toggleColorsWindowByKey()
                }
            }
        }
    }

    func dumpDiagnostics() {
        guard AXIsProcessTrusted() else { promptAccessibility(); return }
        guard let app = logicApp else { log("diagnostics: Logic Pro is not running"); return }
        let axApp = AXUIElementCreateApplication(app.processIdentifier)
        var out = "=== diagnostics ===\n"
        let windows: [AXUIElement] = axAttr(axApp, kAXWindowsAttribute) ?? []
        for w in windows {
            let t: String = axAttr(w, kAXTitleAttribute) ?? "?"
            out += "window '\(t)' frame=\(axFrame(w).map { "\($0)" } ?? "?")\n"
        }
        if let bar: AXUIElement = axAttr(axApp, kAXMenuBarAttribute) {
            var stack: [(AXUIElement, Int, String)] = [(bar, 0, "")]
            while let (el, depth, path) = stack.popLast() {
                let t: String = axAttr(el, kAXTitleAttribute) ?? ""
                let p = t.isEmpty ? path : path + " > " + t
                if t.localizedCaseInsensitiveContains("color") { out += "menu: \(p)\n" }
                if depth < 5 { stack += axChildren(el).map { ($0, depth + 1, p) } }
            }
        }
        if let win = colorWindow(axApp) {
            func walk(_ el: AXUIElement, _ d: Int) {
                let role: String = axAttr(el, kAXRoleAttribute) ?? "?"
                let sub: String = axAttr(el, kAXSubroleAttribute) ?? ""
                let title: String = axAttr(el, kAXTitleAttribute) ?? axAttr(el, kAXDescriptionAttribute) ?? ""
                let kids = axChildren(el)
                out += String(repeating: "  ", count: d) +
                    "\(role) \(sub) '\(title)' \(axFrame(el).map { "\($0)" } ?? "") actions=\(axActions(el)) kids=\(kids.count)\n"
                if d < 6 { kids.prefix(130).forEach { walk($0, d + 1) } }
            }
            walk(win, 0)
        } else {
            out += "Color window is not open\n"
        }
        log(out)
    }
}

func promptAccessibility() {
    let opts = [kAXTrustedCheckOptionPrompt.takeUnretainedValue() as String: true] as CFDictionary
    _ = AXIsProcessTrustedWithOptions(opts)
}

var didPromptAccessibility = false

func promptAccessibilityOnce() {
    guard !didPromptAccessibility else { return }
    didPromptAccessibility = true
    promptAccessibility()
}

// MARK: - Language

/// "ru" / "en" chosen in the menu; otherwise follows the system language.
var isRussian: Bool {
    if let lang = UserDefaults.standard.string(forKey: "language") { return lang == "ru" }
    return Locale.preferredLanguages.first?.hasPrefix("ru") ?? false
}

func L(_ ru: String, _ en: String) -> String { isRussian ? ru : en }

let developerName = "STM Webcode Systems"
let developerSite = "stm-project.ru"

// MARK: - Layouts

enum Layout: String, CaseIterable {
    case vertical, horizontal, square

    var title: String {
        switch self {
        case .vertical: return L("Вертикальная", "Vertical")
        case .horizontal: return L("Горизонтальная", "Horizontal")
        case .square: return L("Квадратная", "Square")
        }
    }

    var next: Layout { Layout.allCases[(Layout.allCases.firstIndex(of: self)! + 1) % Layout.allCases.count] }

    /// Grid size in tiles.
    var grid: (cols: Int, rows: Int) {
        switch self {
        case .vertical: return (logicRows, logicCols)
        case .horizontal: return (logicCols, logicRows)
        case .square: return (logicRows * 2, logicCols / 2)
        }
    }

    /// Square layout = vertical layout folded in two: hues 0-11 on the left, 12-23 on the right.
    func cell(logicRow r: Int, logicCol c: Int) -> (x: Int, y: Int) {
        switch self {
        case .vertical: return (r, c)
        case .horizontal: return (c, r)
        case .square: return (r + (c >= logicCols / 2 ? logicRows : 0), c % (logicCols / 2))
        }
    }

    var blockGap: CGFloat { self == .square ? 3 : 0 }
    var defaultTile: CGSize { self == .square ? CGSize(width: 14, height: 8) : CGSize(width: 12, height: 8) }
    static let minTile = CGSize(width: 6, height: 4)

    func contentSize(tile: CGSize) -> CGSize {
        let (cols, rows) = grid
        return CGSize(width: PaletteView.pad * 2 + CGFloat(cols) * tile.width + CGFloat(cols - 1) * PaletteView.gap + blockGap,
                      height: PaletteView.pad * 2 + PaletteView.header + CGFloat(rows) * tile.height + CGFloat(rows - 1) * PaletteView.gap)
    }

    var defaultSize: CGSize { contentSize(tile: defaultTile) }
    var minSize: CGSize { contentSize(tile: Layout.minTile) }
}

// MARK: - Palette view

final class PaletteView: NSView {
    static let gap: CGFloat = 2
    static let pad: CGFloat = 5
    static let header: CGFloat = 15
    static let collapsedSize = CGSize(width: 26, height: 26)

    var layout = Layout.vertical { didSet { needsDisplay = true } }
    var collapsed = false { didSet { hover = nil; needsDisplay = true } }
    var onPick: ((Int?, Int?) -> Void)?
    var onCollapse: (() -> Void)?
    var onExpand: (() -> Void)?
    var onRotate: (() -> Void)?
    var onResize: ((CGSize) -> Void)?
    var onResizeEnd: (() -> Void)?
    var hover: (Int, Int)?
    var selected: (Int, Int)?

    private enum Drag { case none, resize(start: CGPoint, size: CGSize), move(start: CGPoint, origin: CGPoint, moved: Bool) }
    private var drag = Drag.none

    override var isFlipped: Bool { true }
    override func acceptsFirstMouse(for event: NSEvent?) -> Bool { true }

    var tileSize: CGSize {
        let (cols, rows) = layout.grid
        return CGSize(width: (bounds.width - Self.pad * 2 - CGFloat(cols - 1) * Self.gap - layout.blockGap) / CGFloat(cols),
                      height: (bounds.height - Self.pad * 2 - Self.header - CGFloat(rows - 1) * Self.gap) / CGFloat(rows))
    }

    func tileRect(logicRow: Int, logicCol: Int) -> CGRect {
        let (x, y) = layout.cell(logicRow: logicRow, logicCol: logicCol)
        let t = tileSize
        let extra = layout == .square && x >= logicRows ? layout.blockGap : 0
        return CGRect(x: Self.pad + CGFloat(x) * (t.width + Self.gap) + extra,
                      y: Self.pad + Self.header + CGFloat(y) * (t.height + Self.gap),
                      width: t.width, height: t.height)
    }

    var resetRect: CGRect { CGRect(x: Self.pad, y: Self.pad - 1, width: 14, height: 13) }
    var rotateRect: CGRect { CGRect(x: bounds.midX - 7, y: Self.pad - 1, width: 14, height: 13) }
    var closeRect: CGRect { CGRect(x: bounds.width - Self.pad - 12, y: Self.pad - 1, width: 12, height: 13) }
    var gripRect: CGRect { CGRect(x: bounds.width - 8, y: bounds.height - 8, width: 8, height: 8) }
    var buttonRects: [CGRect] { [resetRect, rotateRect, closeRect] }

    func hit(_ p: CGPoint) -> (Int, Int)? {
        for r in 0..<logicRows {
            for c in 0..<logicCols where tileRect(logicRow: r, logicCol: c).insetBy(dx: -1, dy: -1).contains(p) { return (r, c) }
        }
        return nil
    }

    func drawSymbol(_ name: String, in rect: CGRect, size: CGFloat = 10) {
        guard let img = NSImage(systemSymbolName: name, accessibilityDescription: nil)?
            .withSymbolConfiguration(.init(pointSize: size, weight: .semibold).applying(.init(paletteColors: [NSColor(white: 0.8, alpha: 1)])))
        else { return }
        let s = img.size
        img.draw(in: CGRect(x: rect.midX - s.width / 2, y: rect.midY - s.height / 2, width: s.width, height: s.height))
    }

    override func draw(_ dirtyRect: NSRect) {
        let bg = NSBezierPath(roundedRect: bounds.insetBy(dx: 0.5, dy: 0.5), xRadius: 6, yRadius: 6)
        NSColor(white: 0.11, alpha: 0.97).setFill(); bg.fill()
        NSColor(white: 0.35, alpha: 1).setStroke(); bg.lineWidth = 1; bg.stroke()

        if collapsed {
            drawSymbol("paintpalette", in: bounds, size: 13)
            return
        }

        drawSymbol("arrow.counterclockwise", in: resetRect)
        drawSymbol("rotate.right", in: rotateRect)
        drawSymbol("minus", in: closeRect)

        let t = tileSize
        let radius = min(2, t.height / 4)
        for r in 0..<logicRows {
            for c in 0..<logicCols {
                nsColor(logicColors[r][c]).setFill()
                NSBezierPath(roundedRect: tileRect(logicRow: r, logicCol: c), xRadius: radius, yRadius: radius).fill()
            }
        }
        for (pos, color) in [(selected, NSColor.white), (hover, NSColor(white: 1, alpha: 0.6))] {
            guard let (r, c) = pos else { continue }
            let p = NSBezierPath(roundedRect: tileRect(logicRow: r, logicCol: c).insetBy(dx: -1, dy: -1),
                                 xRadius: radius + 0.5, yRadius: radius + 0.5)
            p.lineWidth = 1.2; color.setStroke(); p.stroke()
        }

        let grip = NSBezierPath()
        for i in 0..<2 {
            let o = CGFloat(i) * 2.5 + 3.5
            grip.move(to: CGPoint(x: bounds.width - o, y: bounds.height - 2))
            grip.line(to: CGPoint(x: bounds.width - 2, y: bounds.height - o))
        }
        NSColor(white: 0.55, alpha: 1).setStroke(); grip.lineWidth = 1; grip.stroke()
    }

    override func resetCursorRects() {
        if !collapsed { addCursorRect(gripRect, cursor: .crosshair) }
    }

    override func updateTrackingAreas() {
        trackingAreas.forEach(removeTrackingArea)
        addTrackingArea(NSTrackingArea(rect: bounds, options: [.activeAlways, .mouseMoved, .mouseEnteredAndExited, .inVisibleRect], owner: self))
    }

    override func mouseMoved(with e: NSEvent) {
        guard !collapsed else { return }
        let h = hit(convert(e.locationInWindow, from: nil))
        if h?.0 != hover?.0 || h?.1 != hover?.1 { hover = h; needsDisplay = true }
    }

    override func mouseExited(with e: NSEvent) { hover = nil; needsDisplay = true }

    override func mouseDown(with e: NSEvent) {
        let p = convert(e.locationInWindow, from: nil)
        guard let w = window else { return }
        if collapsed {
            drag = .move(start: NSEvent.mouseLocation, origin: w.frame.origin, moved: false)
        } else if gripRect.contains(p) {
            drag = .resize(start: NSEvent.mouseLocation, size: bounds.size)
        } else if hit(p) == nil && !buttonRects.contains(where: { $0.contains(p) }) {
            drag = .move(start: NSEvent.mouseLocation, origin: w.frame.origin, moved: false)
        } else {
            drag = .none
        }
    }

    override func mouseDragged(with e: NSEvent) {
        let m = NSEvent.mouseLocation
        switch drag {
        case let .resize(start, size):
            onResize?(CGSize(width: size.width + (m.x - start.x), height: size.height - (m.y - start.y)))
        case let .move(start, origin, moved):
            let dx = m.x - start.x, dy = m.y - start.y
            if moved || abs(dx) + abs(dy) > 3 {
                window?.setFrameOrigin(CGPoint(x: origin.x + dx, y: origin.y + dy))
                drag = .move(start: start, origin: origin, moved: true)
            }
        case .none:
            break
        }
    }

    override func mouseUp(with e: NSEvent) {
        defer { drag = .none }
        switch drag {
        case .resize:
            onResizeEnd?(); return
        case let .move(_, _, moved):
            if moved { return }
            if collapsed { onExpand?(); return }
        case .none:
            break
        }
        let p = convert(e.locationInWindow, from: nil)
        if closeRect.contains(p) { onCollapse?(); return }
        if rotateRect.contains(p) { onRotate?(); return }
        if resetRect.contains(p) { selected = nil; needsDisplay = true; onPick?(nil, nil); return }
        if let (r, c) = hit(p) { selected = (r, c); needsDisplay = true; onPick?(r, c) }
    }

    override func rightMouseDown(with e: NSEvent) {
        if let m = (NSApp.delegate as? AppDelegate)?.menu { NSMenu.popUpContextMenu(m, with: e, for: self) }
    }
}

final class PalettePanel: NSPanel {
    override var canBecomeKey: Bool { false }
    override var canBecomeMain: Bool { false }
}

// MARK: - App

final class AppDelegate: NSObject, NSApplicationDelegate, NSMenuDelegate {
    let bridge = LogicBridge()
    var panel: PalettePanel!
    var view: PaletteView!
    var statusItem: NSStatusItem!
    let menu = NSMenu()
    var loginItem: NSMenuItem!
    var layoutItems: [Layout: NSMenuItem] = [:]
    let defaults = UserDefaults.standard

    var layout: Layout {
        get { Layout(rawValue: defaults.string(forKey: "layout") ?? "") ?? .vertical }
        set { defaults.set(newValue.rawValue, forKey: "layout") }
    }

    func savedSize(_ l: Layout) -> CGSize {
        guard let s = defaults.string(forKey: "size." + l.rawValue) else { return l.defaultSize }
        let size = NSSizeFromString(s)
        return CGSize(width: max(size.width, l.minSize.width), height: max(size.height, l.minSize.height))
    }

    var launchedAtLogin = false

    func applicationWillFinishLaunching(_ n: Notification) {
        let event = NSAppleEventManager.shared().currentAppleEvent
        let loginItemEvent = event?.eventID == kAEOpenApplication &&
            event?.paramDescriptor(forKeyword: keyAEPropData)?.enumCodeValue == keyAELaunchedAsLogInItem
        launchedAtLogin = loginItemEvent || ProcessInfo.processInfo.systemUptime < 300
    }

    func applicationDidFinishLaunching(_ n: Notification) {
        view = PaletteView(frame: CGRect(origin: .zero, size: savedSize(layout)))
        view.layout = layout
        view.onPick = { [weak self] r, c in self?.bridge.apply(row: r, col: c) }
        view.onCollapse = { [weak self] in self?.setCollapsed(true) }
        view.onExpand = { [weak self] in self?.setCollapsed(false) }
        view.onRotate = { [weak self] in self?.setLayout(self!.layout.next) }
        view.onResize = { [weak self] size in self?.resize(to: size) }
        view.onResizeEnd = { [weak self] in
            guard let self else { return }
            self.defaults.set(NSStringFromSize(self.panel.frame.size), forKey: "size." + self.layout.rawValue)
        }

        panel = PalettePanel(contentRect: view.frame, styleMask: [.borderless, .nonactivatingPanel],
                             backing: .buffered, defer: false)
        panel.contentView = view
        panel.isOpaque = false
        panel.backgroundColor = .clear
        panel.hasShadow = true
        panel.level = NSWindow.Level(rawValue: NSWindow.Level.floating.rawValue + 1)
        panel.hidesOnDeactivate = false
        panel.isFloatingPanel = true
        panel.becomesKeyOnlyIfNeeded = true
        panel.collectionBehavior = [.canJoinAllSpaces, .fullScreenAuxiliary]
        if let top = defaults.string(forKey: "topLeft").map(NSPointFromString) {
            panel.setFrameTopLeftPoint(top)
        } else if let s = NSScreen.main?.visibleFrame {
            panel.setFrameTopLeftPoint(CGPoint(x: s.maxX - 120, y: s.maxY - 80))
        }
        NotificationCenter.default.addObserver(self, selector: #selector(panelMoved), name: NSWindow.didMoveNotification, object: panel)
        if bridge.logicApp != nil || !launchedAtLogin { panel.orderFrontRegardless() }

        let ws = NSWorkspace.shared.notificationCenter
        ws.addObserver(self, selector: #selector(appLaunched(_:)), name: NSWorkspace.didLaunchApplicationNotification, object: nil)
        ws.addObserver(self, selector: #selector(appTerminated(_:)), name: NSWorkspace.didTerminateApplicationNotification, object: nil)

        menu.delegate = self
        buildMenu()

        statusItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.variableLength)
        statusItem.button?.image = NSImage(systemSymbolName: "paintpalette", accessibilityDescription: "Logic Palette")
        statusItem.menu = menu

        if !defaults.bool(forKey: "loginItemConfigured") {
            defaults.set(true, forKey: "loginItemConfigured")
            setLoginItem(true)
        }

        if !AXIsProcessTrusted() { promptAccessibilityOnce() }
        log("started, trusted=\(AXIsProcessTrusted()), path=\(Bundle.main.bundlePath)")
    }

    func buildMenu() {
        menu.removeAllItems()
        layoutItems = [:]
        menu.addItem(withTitle: L("Показать / скрыть палитру", "Show / Hide Palette"), action: #selector(togglePanel), keyEquivalent: "")
        menu.addItem(.separator())
        for l in Layout.allCases {
            let item = menu.addItem(withTitle: l.title, action: #selector(chooseLayout(_:)), keyEquivalent: "")
            item.representedObject = l.rawValue
            layoutItems[l] = item
        }
        menu.addItem(withTitle: L("Исходный размер", "Default Size"), action: #selector(resetSize), keyEquivalent: "")
        menu.addItem(.separator())
        loginItem = menu.addItem(withTitle: L("Запускать при входе в систему", "Launch at Login"), action: #selector(toggleLoginItem), keyEquivalent: "")
        menu.addItem(withTitle: L("Разрешение Accessibility…", "Accessibility Permission…"), action: #selector(openAccessibility), keyEquivalent: "")
        menu.addItem(withTitle: L("Записать диагностику в лог", "Write Diagnostics to Log"), action: #selector(diagnostics), keyEquivalent: "")
        menu.addItem(.separator())
        menu.addItem(withTitle: isRussian ? "Switch to English" : "Переключить на русский", action: #selector(toggleLanguage), keyEquivalent: "")
        menu.addItem(.separator())
        menu.addItem(withTitle: L("О программе Logic Palette", "About Logic Palette"), action: #selector(showAbout), keyEquivalent: "")
        menu.addItem(withTitle: "\(developerName) — \(developerSite)", action: #selector(openDeveloperSite), keyEquivalent: "")
        menu.addItem(.separator())
        menu.addItem(withTitle: L("Выход", "Quit"), action: #selector(NSApp.terminate(_:)), keyEquivalent: "q")
        menu.items.forEach { if $0.action != #selector(NSApp.terminate(_:)) { $0.target = self } }
    }

    @objc func showAbout() {
        let credits = NSMutableAttributedString(
            string: L("Разработчик: ", "Developer: ") + developerName + "\n",
            attributes: [.font: NSFont.systemFont(ofSize: 11), .foregroundColor: NSColor.labelColor])
        credits.append(NSAttributedString(string: developerSite, attributes: [
            .font: NSFont.systemFont(ofSize: 11), .link: URL(string: "https://\(developerSite)")!]))
        let centered = NSMutableParagraphStyle()
        centered.alignment = .center
        credits.addAttribute(.paragraphStyle, value: centered, range: NSRange(location: 0, length: credits.length))
        NSApp.activate(ignoringOtherApps: true)
        NSApp.orderFrontStandardAboutPanel(options: [.credits: credits])
    }

    @objc func openDeveloperSite() {
        NSWorkspace.shared.open(URL(string: "https://\(developerSite)")!)
    }

    @objc func toggleLanguage() {
        defaults.set(isRussian ? "en" : "ru", forKey: "language")
        buildMenu()
    }

    func menuNeedsUpdate(_ menu: NSMenu) {
        for (l, item) in layoutItems { item.state = l == layout ? .on : .off }
        loginItem.state = SMAppService.mainApp.status == .enabled ? .on : .off
    }

    func applicationShouldHandleReopen(_ sender: NSApplication, hasVisibleWindows flag: Bool) -> Bool {
        showPanel()
        return false
    }

    func isLogic(_ n: Notification) -> Bool {
        (n.userInfo?[NSWorkspace.applicationUserInfoKey] as? NSRunningApplication)?.bundleIdentifier == LogicBridge.bundleID
    }

    @objc func appLaunched(_ n: Notification) {
        if isLogic(n) { showPanel() }
    }

    @objc func appTerminated(_ n: Notification) {
        if isLogic(n) { panel.orderOut(nil) }
    }

    @objc func panelMoved() {
        defaults.set(NSStringFromPoint(CGPoint(x: panel.frame.minX, y: panel.frame.maxY)), forKey: "topLeft")
    }

    func setPanelSize(_ size: CGSize) {
        let f = panel.frame
        panel.setFrame(CGRect(x: f.minX, y: f.maxY - size.height, width: size.width, height: size.height), display: true)
        view.frame = CGRect(origin: .zero, size: size)
        view.needsDisplay = true
        panel.invalidateCursorRects(for: view)
    }

    func resize(to size: CGSize) {
        let m = layout.minSize
        setPanelSize(CGSize(width: max(m.width, size.width.rounded()), height: max(m.height, size.height.rounded())))
    }

    func showPanel() {
        setCollapsed(false)
        panel.orderFrontRegardless()
    }

    func setCollapsed(_ c: Bool) {
        guard view.collapsed != c else { return }
        view.collapsed = c
        setPanelSize(c ? PaletteView.collapsedSize : savedSize(layout))
    }

    func setLayout(_ l: Layout) {
        layout = l
        view.layout = l
        view.collapsed = false
        setPanelSize(savedSize(l))
    }

    @objc func chooseLayout(_ sender: NSMenuItem) {
        if let l = (sender.representedObject as? String).flatMap(Layout.init(rawValue:)) { setLayout(l) }
        showPanel()
    }

    @objc func resetSize() {
        defaults.removeObject(forKey: "size." + layout.rawValue)
        view.collapsed = false
        setPanelSize(layout.defaultSize)
        showPanel()
    }

    @objc func togglePanel() {
        if panel.isVisible && !view.collapsed { panel.orderOut(nil) } else { showPanel() }
    }

    func setLoginItem(_ on: Bool) {
        do {
            if on { try SMAppService.mainApp.register() } else { try SMAppService.mainApp.unregister() }
        } catch {
            log("login item \(on ? "register" : "unregister") failed: \(error)")
        }
    }

    @objc func toggleLoginItem() {
        setLoginItem(SMAppService.mainApp.status != .enabled)
    }

    @objc func openAccessibility() {
        promptAccessibility()
        NSWorkspace.shared.open(URL(string: "x-apple.systempreferences:com.apple.preference.security?Privacy_Accessibility")!)
    }

    @objc func diagnostics() {
        bridge.dumpDiagnostics()
        NSWorkspace.shared.open(logURL)
    }
}

let app = NSApplication.shared
let delegate = AppDelegate()
app.delegate = delegate
app.setActivationPolicy(.accessory)
app.run()

