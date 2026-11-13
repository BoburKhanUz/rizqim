// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'member3.g.dart';

@JsonSerializable()
class Member3 {
  const Member3({
    required this.uuid,
    required this.name,
    required this.code,
    required this.isActive,
  });

  factory Member3.fromJson(Map<String, Object?> json) =>
      _$Member3FromJson(json);

  final String uuid;
  final String name;
  final String code;
  @JsonKey(name: 'is_active')
  final bool isActive;

  Map<String, Object?> toJson() => _$Member3ToJson(this);
}
