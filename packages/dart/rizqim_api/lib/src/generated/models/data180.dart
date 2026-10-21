// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device18.dart';
import 'store3.dart';
import 'branch12.dart';
import 'branches2.dart';

part 'data180.g.dart';

@JsonSerializable()
class Data180 {
  const Data180({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data180.fromJson(Map<String, Object?> json) =>
      _$Data180FromJson(json);

  final Device18 device;
  final Store3? store;
  final Branch12? branch;
  final List<Branches2> branches;

  Map<String, Object?> toJson() => _$Data180ToJson(this);
}
