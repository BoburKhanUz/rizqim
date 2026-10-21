// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_payment_method2.g.dart';

@JsonSerializable()
class ByPaymentMethod2 {
  const ByPaymentMethod2({
    required this.method,
    required this.orders,
    required this.revenue,
  });

  factory ByPaymentMethod2.fromJson(Map<String, Object?> json) =>
      _$ByPaymentMethod2FromJson(json);

  final String method;
  final int orders;
  final String revenue;

  Map<String, Object?> toJson() => _$ByPaymentMethod2ToJson(this);
}
