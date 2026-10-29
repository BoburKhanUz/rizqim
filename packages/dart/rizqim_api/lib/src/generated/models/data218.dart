// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device24.dart';
import 'store3.dart';
import 'branch14.dart';
import 'branches3.dart';

part 'data218.g.dart';

@JsonSerializable()
class Data218 {
  const Data218({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data218.fromJson(Map<String, Object?> json) =>
      _$Data218FromJson(json);

  final Device24 device;
  final Store3? store;
  final Branch14? branch;
  final List<Branches3> branches;

  Map<String, Object?> toJson() => _$Data218ToJson(this);
}
