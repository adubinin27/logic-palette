#!/bin/zsh
# Usage: ./build.sh [--install] [--dmg]
set -e
cd "${0:A:h}"
APP="build/Logic Palette.app"
rm -rf "$APP"
mkdir -p "$APP/Contents/MacOS" "$APP/Contents/Resources"
cp Info.plist "$APP/Contents/"
cp AppIcon.icns "$APP/Contents/Resources/"
swiftc -O -target x86_64-apple-macos13 main.swift -o "$APP/Contents/MacOS/LogicPalette.x86_64"
swiftc -O -target arm64-apple-macos13 main.swift -o "$APP/Contents/MacOS/LogicPalette.arm64"
lipo -create "$APP/Contents/MacOS/LogicPalette."{x86_64,arm64} -output "$APP/Contents/MacOS/LogicPalette"
rm "$APP/Contents/MacOS/LogicPalette."{x86_64,arm64}
# A stable identity keeps the Accessibility permission valid across rebuilds.
# Create a self-signed code-signing certificate with this name once (see README, "Сборка из исходников").
IDENTITY="LogicPalette Local Signing"
if security find-identity -p codesigning | grep -q "$IDENTITY"; then
  codesign --force --sign "$IDENTITY" "$APP"
else
  echo "warning: '$IDENTITY' not found, signing ad-hoc"
  codesign --force --sign - "$APP"
fi
echo "Built: $PWD/$APP"

if [[ " $* " == *" --install "* ]]; then
  pkill -x LogicPalette || true
  rm -rf "/Applications/Logic Palette.app"
  ditto "$APP" "/Applications/Logic Palette.app"
  echo "Installed: /Applications/Logic Palette.app"
fi

if [[ " $* " == *" --pkg "* ]]; then
  VERSION=$(/usr/libexec/PlistBuddy -c "Print :CFBundleShortVersionString" Info.plist)
  ROOT=$(mktemp -d)
  ditto "$APP" "$ROOT/Logic Palette.app"
  pkgbuild --analyze --root "$ROOT" "$ROOT.plist" >/dev/null
  /usr/libexec/PlistBuddy -c "Set :0:BundleIsRelocatable false" "$ROOT.plist"
  PKG="../Logic Palette $VERSION.pkg"
  rm -f "$PKG"
  pkgbuild --root "$ROOT" --component-plist "$ROOT.plist" --install-location /Applications \
    --identifier local.adubinin.LogicPalette.pkg --version "$VERSION" "$PKG" >/dev/null
  rm -rf "$ROOT" "$ROOT.plist"
  echo "PKG: ${PKG:A}"
  [[ -d ../site/downloads ]] && cp "$PKG" "../site/downloads/Logic-Palette-$VERSION.pkg" && echo "Site: site/downloads/Logic-Palette-$VERSION.pkg"
fi

if [[ " $* " == *" --dmg "* ]]; then
  VERSION=$(/usr/libexec/PlistBuddy -c "Print :CFBundleShortVersionString" Info.plist)
  STAGE=$(mktemp -d)
  ditto "$APP" "$STAGE/Logic Palette.app"
  ln -s /Applications "$STAGE/Applications"
  DMG="../Logic Palette $VERSION.dmg"
  rm -f "$DMG"
  hdiutil create -volname "Logic Palette" -srcfolder "$STAGE" -ov -format UDZO "$DMG" >/dev/null
  rm -rf "$STAGE"
  echo "DMG: ${DMG:A}"
  [[ -d ../site/downloads ]] && cp "$DMG" "../site/downloads/Logic-Palette-$VERSION.dmg" && echo "Site: site/downloads/Logic-Palette-$VERSION.dmg"
fi
