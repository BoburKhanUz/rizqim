// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kind5.dart';

part 'lines32.g.dart';

@JsonSerializable()
class Lines32 {
  const Lines32({required this.kind, required this.label, required this.value});

  factory Lines32.fromJson(Map<String, Object?> json) =>
      _$Lines32FromJson(json);

  final Kind5 kind;
  final String label;
  final String value;

  Map<String, Object?> toJson() => _$Lines32ToJson(this);
}
