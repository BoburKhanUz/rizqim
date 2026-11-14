// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'morning.g.dart';

@JsonSerializable()
class Morning {
  const Morning({required this.start, required this.end});

  factory Morning.fromJson(Map<String, Object?> json) =>
      _$MorningFromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$MorningToJson(this);
}
