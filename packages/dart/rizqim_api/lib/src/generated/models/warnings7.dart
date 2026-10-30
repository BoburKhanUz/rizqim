// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'warnings7.g.dart';

@JsonSerializable()
class Warnings7 {
  const Warnings7({
    required this.type,
    required this.userId,
    required this.name,
    this.count,
  });

  factory Warnings7.fromJson(Map<String, Object?> json) =>
      _$Warnings7FromJson(json);

  final String type;
  @JsonKey(name: 'user_id')
  final int? userId;
  final String? name;
  final int? count;

  Map<String, Object?> toJson() => _$Warnings7ToJson(this);
}
