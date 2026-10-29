// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'locks2.g.dart';

@JsonSerializable()
class Locks2 {
  const Locks2({required this.serviceChargeZero});

  factory Locks2.fromJson(Map<String, Object?> json) => _$Locks2FromJson(json);

  @JsonKey(name: 'service_charge_zero')
  final bool serviceChargeZero;

  Map<String, Object?> toJson() => _$Locks2ToJson(this);
}
