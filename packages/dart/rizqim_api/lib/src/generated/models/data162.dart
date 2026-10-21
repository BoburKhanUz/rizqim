// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift.dart';

part 'data162.g.dart';

@JsonSerializable()
class Data162 {
  const Data162({required this.shift, required this.report});

  factory Data162.fromJson(Map<String, Object?> json) =>
      _$Data162FromJson(json);

  final Shift? shift;
  final Map<String, dynamic>? report;

  Map<String, Object?> toJson() => _$Data162ToJson(this);
}
