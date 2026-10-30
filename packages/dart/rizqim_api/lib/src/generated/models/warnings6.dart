// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'warnings6.g.dart';

@JsonSerializable()
class Warnings6 {
  const Warnings6({
    required this.type,
    required this.userId,
    required this.name,
    this.count,
  });

  factory Warnings6.fromJson(Map<String, Object?> json) =>
      _$Warnings6FromJson(json);

  final String type;
  @JsonKey(name: 'user_id')
  final int? userId;
  final String? name;
  final int? count;

  Map<String, Object?> toJson() => _$Warnings6ToJson(this);
}
