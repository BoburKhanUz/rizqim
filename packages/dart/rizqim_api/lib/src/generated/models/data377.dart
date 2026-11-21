// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data377.g.dart';

@JsonSerializable()
class Data377 {
  const Data377({
    required this.id,
    required this.name,
    required this.role,
    required this.staffCode,
    required this.photoUrl,
  });

  factory Data377.fromJson(Map<String, Object?> json) =>
      _$Data377FromJson(json);

  final int id;
  final String name;
  final String role;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'photo_url')
  final String? photoUrl;

  Map<String, Object?> toJson() => _$Data377ToJson(this);
}
