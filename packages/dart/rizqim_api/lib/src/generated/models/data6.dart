// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'member2.dart';

part 'data6.g.dart';

@JsonSerializable()
class Data6 {
  const Data6({
    required this.token,
    required this.expiresAt,
    required this.member,
  });

  factory Data6.fromJson(Map<String, Object?> json) => _$Data6FromJson(json);

  final String token;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  final Member2 member;

  Map<String, Object?> toJson() => _$Data6ToJson(this);
}
