// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device25.dart';
import 'store4.dart';
import 'branch22.dart';
import 'branches3.dart';

part 'data319.g.dart';

@JsonSerializable()
class Data319 {
  const Data319({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data319.fromJson(Map<String, Object?> json) =>
      _$Data319FromJson(json);

  final Device25 device;
  final Store4? store;
  final Branch22? branch;
  final List<Branches3> branches;

  Map<String, Object?> toJson() => _$Data319ToJson(this);
}
