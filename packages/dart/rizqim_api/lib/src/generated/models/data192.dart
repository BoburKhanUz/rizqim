// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device24.dart';
import 'store3.dart';
import 'branch13.dart';
import 'branches2.dart';

part 'data192.g.dart';

@JsonSerializable()
class Data192 {
  const Data192({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data192.fromJson(Map<String, Object?> json) =>
      _$Data192FromJson(json);

  final Device24 device;
  final Store3? store;
  final Branch13? branch;
  final List<Branches2> branches;

  Map<String, Object?> toJson() => _$Data192ToJson(this);
}
