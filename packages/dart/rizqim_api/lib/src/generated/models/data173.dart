// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'movement.dart';
import 'shift4.dart';

part 'data173.g.dart';

@JsonSerializable()
class Data173 {
  const Data173({required this.movement, required this.shift});

  factory Data173.fromJson(Map<String, Object?> json) =>
      _$Data173FromJson(json);

  final Movement movement;
  final Shift4 shift;

  Map<String, Object?> toJson() => _$Data173ToJson(this);
}
