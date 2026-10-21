// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift.dart';

part 'data165.g.dart';

@JsonSerializable()
class Data165 {
  const Data165({required this.shift, required this.report});

  factory Data165.fromJson(Map<String, Object?> json) =>
      _$Data165FromJson(json);

  final Shift? shift;
  final Map<String, dynamic>? report;

  Map<String, Object?> toJson() => _$Data165ToJson(this);
}
