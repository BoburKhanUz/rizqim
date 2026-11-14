// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'evening5.g.dart';

@JsonSerializable()
class Evening5 {
  const Evening5({required this.start, required this.end});

  factory Evening5.fromJson(Map<String, Object?> json) =>
      _$Evening5FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$Evening5ToJson(this);
}
