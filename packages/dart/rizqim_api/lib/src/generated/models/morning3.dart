// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'morning3.g.dart';

@JsonSerializable()
class Morning3 {
  const Morning3({required this.start, required this.end});

  factory Morning3.fromJson(Map<String, Object?> json) =>
      _$Morning3FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$Morning3ToJson(this);
}
