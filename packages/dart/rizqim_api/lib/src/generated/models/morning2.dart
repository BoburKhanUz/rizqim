// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'morning2.g.dart';

@JsonSerializable()
class Morning2 {
  const Morning2({required this.start, required this.end});

  factory Morning2.fromJson(Map<String, Object?> json) =>
      _$Morning2FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$Morning2ToJson(this);
}
