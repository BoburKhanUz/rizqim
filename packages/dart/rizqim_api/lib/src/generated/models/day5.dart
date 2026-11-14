// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'day5.g.dart';

@JsonSerializable()
class Day5 {
  const Day5({required this.start, required this.end});

  factory Day5.fromJson(Map<String, Object?> json) => _$Day5FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$Day5ToJson(this);
}
