// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'locks4.dart';

part 'format2.g.dart';

@JsonSerializable()
class Format2 {
  const Format2({
    required this.profile,
    required this.profileLabel,
    required this.mode,
    required this.firstScreen,
    required this.modules,
    required this.locks,
  });

  factory Format2.fromJson(Map<String, Object?> json) =>
      _$Format2FromJson(json);

  final String profile;
  @JsonKey(name: 'profile_label')
  final String profileLabel;
  final String mode;
  @JsonKey(name: 'first_screen')
  final String firstScreen;
  final List<String> modules;
  final Locks4 locks;

  Map<String, Object?> toJson() => _$Format2ToJson(this);
}
