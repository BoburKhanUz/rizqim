// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'movement.dart';
import 'shift4.dart';

part 'data341.g.dart';

@JsonSerializable()
class Data341 {
  const Data341({required this.movement, required this.shift});

  factory Data341.fromJson(Map<String, Object?> json) =>
      _$Data341FromJson(json);

  final Movement movement;
  final Shift4 shift;

  Map<String, Object?> toJson() => _$Data341ToJson(this);
}
