// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'written_off_by.g.dart';

@JsonSerializable()
class WrittenOffBy {
  const WrittenOffBy({required this.id, required this.name});

  factory WrittenOffBy.fromJson(Map<String, Object?> json) =>
      _$WrittenOffByFromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$WrittenOffByToJson(this);
}
