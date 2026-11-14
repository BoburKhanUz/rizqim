// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'orders3.g.dart';

@JsonSerializable()
class Orders3 {
  const Orders3({
    required this.lines,
    required this.discount,
    required this.serviceCharge,
    required this.otherCharges,
    required this.total,
  });

  factory Orders3.fromJson(Map<String, Object?> json) =>
      _$Orders3FromJson(json);

  final String lines;
  final String discount;
  @JsonKey(name: 'service_charge')
  final String serviceCharge;
  @JsonKey(name: 'other_charges')
  final String otherCharges;
  final String total;

  Map<String, Object?> toJson() => _$Orders3ToJson(this);
}
