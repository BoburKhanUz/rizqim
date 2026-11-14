// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'slots6.g.dart';

@JsonSerializable()
class Slots6 {
  const Slots6({required this.start, required this.end});

  factory Slots6.fromJson(Map<String, Object?> json) => _$Slots6FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$Slots6ToJson(this);
}
