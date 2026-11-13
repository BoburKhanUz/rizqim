// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kind4.dart';

part 'lines21.g.dart';

@JsonSerializable()
class Lines21 {
  const Lines21({required this.kind, required this.label, required this.value});

  factory Lines21.fromJson(Map<String, Object?> json) =>
      _$Lines21FromJson(json);

  final Kind4 kind;
  final String label;
  final String value;

  Map<String, Object?> toJson() => _$Lines21ToJson(this);
}
