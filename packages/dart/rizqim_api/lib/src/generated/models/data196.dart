// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'movement.dart';
import 'shift4.dart';

part 'data196.g.dart';

@JsonSerializable()
class Data196 {
  const Data196({required this.movement, required this.shift});

  factory Data196.fromJson(Map<String, Object?> json) =>
      _$Data196FromJson(json);

  final Movement movement;
  final Shift4 shift;

  Map<String, Object?> toJson() => _$Data196ToJson(this);
}
