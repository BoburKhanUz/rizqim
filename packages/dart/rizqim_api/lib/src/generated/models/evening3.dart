// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'evening3.g.dart';

@JsonSerializable()
class Evening3 {
  const Evening3({required this.start, required this.end});

  factory Evening3.fromJson(Map<String, Object?> json) =>
      _$Evening3FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$Evening3ToJson(this);
}
