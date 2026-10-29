// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'locks.g.dart';

@JsonSerializable()
class Locks {
  const Locks({required this.serviceChargeZero});

  factory Locks.fromJson(Map<String, Object?> json) => _$LocksFromJson(json);

  @JsonKey(name: 'service_charge_zero')
  final bool serviceChargeZero;

  Map<String, Object?> toJson() => _$LocksToJson(this);
}
