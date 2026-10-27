// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data15.g.dart';

@JsonSerializable()
class Data15 {
  const Data15({
    required this.type,
    required this.id,
    required this.title,
    required this.subtitle,
    required this.href,
  });

  factory Data15.fromJson(Map<String, Object?> json) => _$Data15FromJson(json);

  final String type;
  final dynamic id;
  final String title;
  final String? subtitle;
  final String href;

  Map<String, Object?> toJson() => _$Data15ToJson(this);
}
