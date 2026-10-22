// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'period7.g.dart';

@JsonSerializable()
class Period7 {
  const Period7({required this.from, required this.to});

  factory Period7.fromJson(Map<String, Object?> json) =>
      _$Period7FromJson(json);

  final String from;
  final String? to;

  Map<String, Object?> toJson() => _$Period7ToJson(this);
}
