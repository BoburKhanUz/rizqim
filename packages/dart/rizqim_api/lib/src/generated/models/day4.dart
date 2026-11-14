// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'day4.g.dart';

@JsonSerializable()
class Day4 {
  const Day4({required this.start, required this.end});

  factory Day4.fromJson(Map<String, Object?> json) => _$Day4FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$Day4ToJson(this);
}
