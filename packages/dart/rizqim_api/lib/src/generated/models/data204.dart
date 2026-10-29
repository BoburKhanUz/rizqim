// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'movement.dart';
import 'shift4.dart';

part 'data204.g.dart';

@JsonSerializable()
class Data204 {
  const Data204({required this.movement, required this.shift});

  factory Data204.fromJson(Map<String, Object?> json) =>
      _$Data204FromJson(json);

  final Movement movement;
  final Shift4 shift;

  Map<String, Object?> toJson() => _$Data204ToJson(this);
}
