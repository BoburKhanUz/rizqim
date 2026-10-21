// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'movement.dart';
import 'shift2.dart';

part 'data167.g.dart';

@JsonSerializable()
class Data167 {
  const Data167({required this.movement, required this.shift});

  factory Data167.fromJson(Map<String, Object?> json) =>
      _$Data167FromJson(json);

  final Movement movement;
  final Shift2 shift;

  Map<String, Object?> toJson() => _$Data167ToJson(this);
}
