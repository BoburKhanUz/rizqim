// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data268.g.dart';

@JsonSerializable()
class Data268 {
  const Data268({
    required this.id,
    required this.name,
    required this.role,
    required this.staffCode,
    required this.photoUrl,
  });

  factory Data268.fromJson(Map<String, Object?> json) =>
      _$Data268FromJson(json);

  final int id;
  final String name;
  final String role;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'photo_url')
  final String? photoUrl;

  Map<String, Object?> toJson() => _$Data268ToJson(this);
}
