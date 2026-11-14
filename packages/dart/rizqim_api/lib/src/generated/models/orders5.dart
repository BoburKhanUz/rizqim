// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'orders5.g.dart';

@JsonSerializable()
class Orders5 {
  const Orders5({
    required this.lines,
    required this.discount,
    required this.serviceCharge,
    required this.otherCharges,
    required this.total,
  });

  factory Orders5.fromJson(Map<String, Object?> json) =>
      _$Orders5FromJson(json);

  final String lines;
  final String discount;
  @JsonKey(name: 'service_charge')
  final String serviceCharge;
  @JsonKey(name: 'other_charges')
  final String otherCharges;
  final String total;

  Map<String, Object?> toJson() => _$Orders5ToJson(this);
}
