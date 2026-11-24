// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device26.dart';
import 'store4.dart';
import 'branch22.dart';
import 'branches4.dart';
import 'app_release.dart';

part 'data380.g.dart';

@JsonSerializable()
class Data380 {
  const Data380({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
    required this.appRelease,
  });

  factory Data380.fromJson(Map<String, Object?> json) =>
      _$Data380FromJson(json);

  final Device26 device;
  final Store4? store;
  final Branch22? branch;
  final List<Branches4> branches;
  @JsonKey(name: 'app_release')
  final AppRelease? appRelease;

  Map<String, Object?> toJson() => _$Data380ToJson(this);
}
