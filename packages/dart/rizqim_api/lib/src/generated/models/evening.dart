// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'evening.g.dart';

@JsonSerializable()
class Evening {
  const Evening({required this.start, required this.end});

  factory Evening.fromJson(Map<String, Object?> json) =>
      _$EveningFromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$EveningToJson(this);
}
