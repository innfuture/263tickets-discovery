// swift-tools-version:5.9
import PackageDescription

let package = Package(
    name: "ExampleAppScannerSDK",
    platforms: [
        .iOS(.v15),
        .macOS(.v12),
    ],
    products: [
        .library(name: "ExampleAppScannerSDK", targets: ["ExampleAppScannerSDK"]),
    ],
    targets: [
        .target(
            name: "ExampleAppScannerSDK",
            path: "Sources/ExampleAppScannerSDK"
        ),
        .testTarget(
            name: "ExampleAppScannerSDKTests",
            dependencies: ["ExampleAppScannerSDK"],
            path: "Tests/ExampleAppScannerSDKTests"
        ),
    ]
)
