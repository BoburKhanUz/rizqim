// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'locks.dart';
import 'module_rows.dart';
import 'profiles.dart';
import 'template.dart';

part 'data108.g.dart';

@JsonSerializable()
class Data108 {
  const Data108({
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

  factory Data108.fromJson(Map<String, Object?> json) =>
      _$Data108FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String profile;
  @JsonKey(name: 'profile_label')
  final String profileLabel;
  final String mode;
  @JsonKey(name: 'first_screen')
  final String firstScreen;
  final List<String> modules;
  final Locks locks;
  @JsonKey(name: 'module_rows')
  final List<ModuleRows> moduleRows;
  final List<Profiles> profiles;
  final Template template;

  Map<String, Object?> toJson() => _$Data108ToJson(this);
}
