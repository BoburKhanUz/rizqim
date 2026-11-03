// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff6.dart';

part 'data160.g.dart';

@JsonSerializable()
class Data160 {
  const Data160({required this.staff, required this.inviteLink});

  factory Data160.fromJson(Map<String, Object?> json) =>
      _$Data160FromJson(json);

  final Staff6 staff;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data160ToJson(this);
}
