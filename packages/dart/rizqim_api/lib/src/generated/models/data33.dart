// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user8.dart';

part 'data33.g.dart';

@JsonSerializable()
class Data33 {
  const Data33({
    required this.token,
    required this.expiresAt,
    required this.user,
    required this.permissions,
  });

  factory Data33.fromJson(Map<String, Object?> json) => _$Data33FromJson(json);

  final String token;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  final User8 user;
  final List<String> permissions;

  Map<String, Object?> toJson() => _$Data33ToJson(this);
}
