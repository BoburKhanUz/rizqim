// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user.dart';

part 'data84.g.dart';

@JsonSerializable()
class Data84 {
  const Data84({required this.employee, required this.inviteLink});

  factory Data84.fromJson(Map<String, Object?> json) => _$Data84FromJson(json);

  final User employee;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data84ToJson(this);
}
