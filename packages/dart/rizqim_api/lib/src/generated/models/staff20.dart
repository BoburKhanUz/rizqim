// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'staff20.g.dart';

@JsonSerializable()
class Staff20 {
  const Staff20({
    required this.id,
    required this.name,
    required this.role,
    required this.photoUrl,
  });

  factory Staff20.fromJson(Map<String, Object?> json) =>
      _$Staff20FromJson(json);

  final int id;
  final String name;
  final String role;
  @JsonKey(name: 'photo_url')
  final String? photoUrl;

  Map<String, Object?> toJson() => _$Staff20ToJson(this);
}
