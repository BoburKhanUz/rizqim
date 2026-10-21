// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff.dart';

part 'data72.g.dart';

@JsonSerializable()
class Data72 {
  const Data72({required this.staff, required this.inviteLink});

  factory Data72.fromJson(Map<String, Object?> json) => _$Data72FromJson(json);

  final Staff staff;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data72ToJson(this);
}
