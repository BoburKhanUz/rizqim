// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'pos.dart';
import 'kds.dart';
import 'waiter.dart';

part 'devices.g.dart';

@JsonSerializable()
class Devices {
  const Devices({required this.pos, required this.kds, required this.waiter});

  factory Devices.fromJson(Map<String, Object?> json) =>
      _$DevicesFromJson(json);

  final Pos pos;
  final Kds kds;
  final Waiter waiter;

  Map<String, Object?> toJson() => _$DevicesToJson(this);
}
