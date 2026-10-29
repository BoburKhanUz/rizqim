// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'locks3.dart';
import 'module_rows3.dart';
import 'profiles3.dart';
import 'template3.dart';

part 'format.g.dart';

@JsonSerializable()
class Format {
  const Format({
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

  factory Format.fromJson(Map<String, Object?> json) => _$FormatFromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String profile;
  @JsonKey(name: 'profile_label')
  final String profileLabel;
  final String mode;
  @JsonKey(name: 'first_screen')
  final String firstScreen;
  final List<String> modules;
  final Locks3 locks;
  @JsonKey(name: 'module_rows')
  final List<ModuleRows3> moduleRows;
  final List<Profiles3> profiles;
  final Template3 template;

  Map<String, Object?> toJson() => _$FormatToJson(this);
}
