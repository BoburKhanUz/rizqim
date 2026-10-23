// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device19.dart';
import 'store3.dart';
import 'branch13.dart';
import 'branches2.dart';

part 'data188.g.dart';

@JsonSerializable()
class Data188 {
  const Data188({
    required this.device,
    required this.store,
    required this.branch,
    required this.branches,
  });

  factory Data188.fromJson(Map<String, Object?> json) =>
      _$Data188FromJson(json);

  final Device19 device;
  final Store3? store;
  final Branch13? branch;
  final List<Branches2> branches;

  Map<String, Object?> toJson() => _$Data188ToJson(this);
}
