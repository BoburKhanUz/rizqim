// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'evening2.g.dart';

@JsonSerializable()
class Evening2 {
  const Evening2({required this.start, required this.end});

  factory Evening2.fromJson(Map<String, Object?> json) =>
      _$Evening2FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$Evening2ToJson(this);
}
