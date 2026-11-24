// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items.dart';
import 'pagination.dart';

part 'data23.g.dart';

@JsonSerializable()
class Data23 {
  const Data23({
    required this.items,
    required this.unreadCount,
    required this.pagination,
  });

  factory Data23.fromJson(Map<String, Object?> json) => _$Data23FromJson(json);

  final List<Items> items;
  @JsonKey(name: 'unread_count')
  final int unreadCount;
  final Pagination pagination;

  Map<String, Object?> toJson() => _$Data23ToJson(this);
}
