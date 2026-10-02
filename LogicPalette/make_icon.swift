// Renders AppIcon.icns: swift make_icon.swift
import Cocoa

let colors: [[UInt32]] = [
    [0xB94624, 0xBE7F30, 0xC9C943, 0x91C73F, 0x5FC64A, 0x5FC685],
    [0x60C6C8, 0x4D95CA, 0x4867CF, 0x5A45CE, 0x8439CA, 0xB830C3],
    [0x964125, 0x9A6C2D, 0xA3A23A, 0x79A137, 0x4FA042, 0x4FA071],
    [0x4FA1A2, 0x437BA2, 0x445BA8, 0x5141A7, 0x7138A5, 0x952C9E],
    [0x733824, 0x775729, 0x7D7C30, 0x607B2F, 0x3F7B38, 0x3F7B5A],
    [0x3F7B7C, 0x39627D, 0x3B4B80, 0x453A80, 0x5A337E, 0x722879],
]

func render(_ px: Int) -> NSBitmapImageRep {
    let rep = NSBitmapImageRep(bitmapDataPlanes: nil, pixelsWide: px, pixelsHigh: px, bitsPerSample: 8, samplesPerPixel: 4,
                               hasAlpha: true, isPlanar: false, colorSpaceName: .deviceRGB, bytesPerRow: 0, bitsPerPixel: 0)!
    NSGraphicsContext.saveGraphicsState()
    NSGraphicsContext.current = NSGraphicsContext(bitmapImageRep: rep)
    let s = CGFloat(px) / 1024
    let body = CGRect(x: 100 * s, y: 100 * s, width: 824 * s, height: 824 * s)
    let bg = NSBezierPath(roundedRect: body, xRadius: 185 * s, yRadius: 185 * s)
    NSGradient(starting: NSColor(white: 0.20, alpha: 1), ending: NSColor(white: 0.08, alpha: 1))!.draw(in: bg, angle: -90)
    NSColor(white: 0.38, alpha: 1).setStroke(); bg.lineWidth = 6 * s; bg.stroke()

    let pad: CGFloat = 120 * s, gap: CGFloat = 22 * s
    let n = CGFloat(colors.count)
    let tw = (body.width - pad * 2 - gap * (n - 1)) / n
    let th = tw * 0.66
    let gridH = th * n + gap * (n - 1)
    let top = body.midY + gridH / 2
    for (r, row) in colors.enumerated() {
        for (c, hex) in row.enumerated() {
            NSColor(srgbRed: CGFloat((hex >> 16) & 0xFF) / 255, green: CGFloat((hex >> 8) & 0xFF) / 255,
                    blue: CGFloat(hex & 0xFF) / 255, alpha: 1).setFill()
            let rect = CGRect(x: body.minX + pad + CGFloat(c) * (tw + gap), y: top - th - CGFloat(r) * (th + gap), width: tw, height: th)
            NSBezierPath(roundedRect: rect, xRadius: 14 * s, yRadius: 14 * s).fill()
        }
    }
    NSGraphicsContext.restoreGraphicsState()
    return rep
}

let dir = URL(fileURLWithPath: CommandLine.arguments.count > 1 ? CommandLine.arguments[1] : "AppIcon.iconset")
try? FileManager.default.createDirectory(at: dir, withIntermediateDirectories: true)
for base in [16, 32, 128, 256, 512] {
    for scale in [1, 2] {
        let name = scale == 1 ? "icon_\(base)x\(base).png" : "icon_\(base)x\(base)@2x.png"
        try! render(base * scale).representation(using: .png, properties: [:])!.write(to: dir.appendingPathComponent(name))
    }
}
