// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'period8.g.dart';

@JsonSerializable()
class Period8 {
  const Period8({required this.from, required this.to});

  factory Period8.fromJson(Map<String, Object?> json) =>
      _$Period8FromJson(json);

  final String from;
  final String? to;

  Map<String, Object?> toJson() => _$Period8ToJson(this);
}
