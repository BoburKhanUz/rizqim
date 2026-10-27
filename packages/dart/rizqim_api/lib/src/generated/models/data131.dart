// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff.dart';

part 'data131.g.dart';

@JsonSerializable()
class Data131 {
  const Data131({required this.staff, required this.inviteLink});

  factory Data131.fromJson(Map<String, Object?> json) =>
      _$Data131FromJson(json);

  final Staff staff;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data131ToJson(this);
}
