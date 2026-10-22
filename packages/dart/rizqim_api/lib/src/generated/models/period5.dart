// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'period5.g.dart';

@JsonSerializable()
class Period5 {
  const Period5({required this.from, required this.to});

  factory Period5.fromJson(Map<String, Object?> json) =>
      _$Period5FromJson(json);

  final String from;
  final String? to;

  Map<String, Object?> toJson() => _$Period5ToJson(this);
}
