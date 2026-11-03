// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device25.dart';
import 'store4.dart';
import 'branch16.dart';
import 'branches3.dart';

part 'data290.g.dart';

@JsonSerializable()
class Data290 {
  const Data290({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data290.fromJson(Map<String, Object?> json) =>
      _$Data290FromJson(json);

  final Device25 device;
  final Store4? store;
  final Branch16? branch;
  final List<Branches3> branches;

  Map<String, Object?> toJson() => _$Data290ToJson(this);
}
