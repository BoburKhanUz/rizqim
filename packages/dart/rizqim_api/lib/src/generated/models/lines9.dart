// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kind5.dart';

part 'lines9.g.dart';

@JsonSerializable()
class Lines9 {
  const Lines9({required this.kind, required this.label, required this.value});

  factory Lines9.fromJson(Map<String, Object?> json) => _$Lines9FromJson(json);

  final Kind5 kind;
  final String label;
  final String value;

  Map<String, Object?> toJson() => _$Lines9ToJson(this);
}
