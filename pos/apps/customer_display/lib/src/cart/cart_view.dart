// P6 hardware: the cart views moved to pos_ui (package:pos_ui/customer_display.dart) so the cashier's second screen
// (Sunmi D2, Android Presentation) draws the same thing; this file keeps the app's imports working.
export 'package:pos_ui/customer_display.dart'
    show CartView, PaidView, paymentMethodLabel;
