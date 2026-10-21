// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'opened_by7.g.dart';

@JsonSerializable()
class OpenedBy7 {
  const OpenedBy7({required this.id, required this.name});

  factory OpenedBy7.fromJson(Map<String, Object?> json) =>
      _$OpenedBy7FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$OpenedBy7ToJson(this);
}
