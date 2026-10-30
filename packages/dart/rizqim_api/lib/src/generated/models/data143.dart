// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff6.dart';

part 'data143.g.dart';

@JsonSerializable()
class Data143 {
  const Data143({required this.staff, required this.inviteLink});

  factory Data143.fromJson(Map<String, Object?> json) =>
      _$Data143FromJson(json);

  final Staff6 staff;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data143ToJson(this);
}
