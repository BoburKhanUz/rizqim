// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device25.dart';
import 'store4.dart';
import 'branch13.dart';
import 'branches2.dart';

part 'data216.g.dart';

@JsonSerializable()
class Data216 {
  const Data216({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data216.fromJson(Map<String, Object?> json) =>
      _$Data216FromJson(json);

  final Device25 device;
  final Store4? store;
  final Branch13? branch;
  final List<Branches2> branches;

  Map<String, Object?> toJson() => _$Data216ToJson(this);
}
