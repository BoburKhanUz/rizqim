// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch8.dart';
import 'setup.dart';
import 'devices2.dart';

part 'branches3.g.dart';

@JsonSerializable()
class Branches3 {
  const Branches3({
    required this.branch,
    required this.moduleEnabled,
    required this.setup,
    required this.devices,
  });

  factory Branches3.fromJson(Map<String, Object?> json) =>
      _$Branches3FromJson(json);

  final Branch8 branch;
  @JsonKey(name: 'module_enabled')
  final bool moduleEnabled;
  final Setup setup;
  final List<Devices2> devices;

  Map<String, Object?> toJson() => _$Branches3ToJson(this);
}
