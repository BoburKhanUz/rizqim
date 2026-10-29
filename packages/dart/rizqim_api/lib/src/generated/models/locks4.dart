// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'locks4.g.dart';

@JsonSerializable()
class Locks4 {
  const Locks4({required this.serviceChargeZero});

  factory Locks4.fromJson(Map<String, Object?> json) => _$Locks4FromJson(json);

  @JsonKey(name: 'service_charge_zero')
  final bool serviceChargeZero;

  Map<String, Object?> toJson() => _$Locks4ToJson(this);
}
