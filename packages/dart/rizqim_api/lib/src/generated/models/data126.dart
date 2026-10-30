// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'locks2.dart';
import 'module_rows2.dart';
import 'profiles2.dart';
import 'template2.dart';

part 'data126.g.dart';

@JsonSerializable()
class Data126 {
  const Data126({
    required this.branchId,
    required this.profile,
    required this.profileLabel,
    required this.mode,
    required this.firstScreen,
    required this.modules,
    required this.locks,
    required this.moduleRows,
    required this.profiles,
    required this.template,
  });

  factory Data126.fromJson(Map<String, Object?> json) =>
      _$Data126FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String profile;
  @JsonKey(name: 'profile_label')
  final String profileLabel;
  final String mode;
  @JsonKey(name: 'first_screen')
  final String firstScreen;
  final List<String> modules;
  final Locks2 locks;
  @JsonKey(name: 'module_rows')
  final List<ModuleRows2> moduleRows;
  final List<Profiles2> profiles;
  final Template2 template;

  Map<String, Object?> toJson() => _$Data126ToJson(this);
}
