// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device10.dart';
import 'store2.dart';
import 'branch11.dart';
import 'branches2.dart';

part 'data94.g.dart';

@JsonSerializable()
class Data94 {
  const Data94({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data94.fromJson(Map<String, Object?> json) => _$Data94FromJson(json);

  final Device10 device;
  final Store2? store;
  final Branch11? branch;
  final List<Branches2> branches;

  Map<String, Object?> toJson() => _$Data94ToJson(this);
}
