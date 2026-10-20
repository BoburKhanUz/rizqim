// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_payment_method.g.dart';

@JsonSerializable()
class ByPaymentMethod {
  const ByPaymentMethod({
    required this.method,
    required this.orders,
    required this.revenue,
  });

  factory ByPaymentMethod.fromJson(Map<String, Object?> json) =>
      _$ByPaymentMethodFromJson(json);

  final String method;
  final int orders;
  final num revenue;

  Map<String, Object?> toJson() => _$ByPaymentMethodToJson(this);
}
