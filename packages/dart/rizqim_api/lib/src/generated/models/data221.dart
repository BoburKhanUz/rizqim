// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device25.dart';
import 'store4.dart';
import 'branch15.dart';
import 'branches3.dart';

part 'data221.g.dart';

@JsonSerializable()
class Data221 {
  const Data221({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data221.fromJson(Map<String, Object?> json) =>
      _$Data221FromJson(json);

  final Device25 device;
  final Store4? store;
  final Branch15? branch;
  final List<Branches3> branches;

  Map<String, Object?> toJson() => _$Data221ToJson(this);
}
