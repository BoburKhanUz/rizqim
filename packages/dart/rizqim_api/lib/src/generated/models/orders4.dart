// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'orders4.g.dart';

@JsonSerializable()
class Orders4 {
  const Orders4({
    required this.lines,
    required this.discount,
    required this.serviceCharge,
    required this.otherCharges,
    required this.total,
  });

  factory Orders4.fromJson(Map<String, Object?> json) =>
      _$Orders4FromJson(json);

  final String lines;
  final String discount;
  @JsonKey(name: 'service_charge')
  final String serviceCharge;
  @JsonKey(name: 'other_charges')
  final String otherCharges;
  final String total;

  Map<String, Object?> toJson() => _$Orders4ToJson(this);
}
