// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff.dart';

part 'data110.g.dart';

@JsonSerializable()
class Data110 {
  const Data110({required this.staff, required this.inviteLink});

  factory Data110.fromJson(Map<String, Object?> json) =>
      _$Data110FromJson(json);

  final Staff staff;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data110ToJson(this);
}
