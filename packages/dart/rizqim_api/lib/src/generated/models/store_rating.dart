// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';
import 'store.dart';
import 'user.dart';

part 'store_rating.g.dart';

@JsonSerializable()
class StoreRating {
  const StoreRating({
    required this.id,
    required this.userId,
    required this.storeId,
    required this.orderId,
    required this.rating,
    required this.comment,
    required this.createdAt,
    required this.updatedAt,
    this.user,
    this.store,
    this.order,
  });

  factory StoreRating.fromJson(Map<String, Object?> json) =>
      _$StoreRatingFromJson(json);

  final int id;
  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'order_id')
  final int? orderId;
  final int rating;
  final String? comment;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final User? user;
  final Store? store;
  final Order? order;

  Map<String, Object?> toJson() => _$StoreRatingToJson(this);
}
